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


class CControllerAuditSettingsUpdate extends CController {

	protected function init(): void {
		$this->setPostContentType(self::POST_CONTENT_TYPE_JSON);
		$this->setInputValidationMethod(self::INPUT_VALIDATION_FORM);
	}

	public static function getValidationRules(): array {
		return ['object', 'fields' => [
			'auditlog_enabled' => ['boolean', 'required'],
			'auditlog_mode' => ['boolean', 'required', 'when' => ['auditlog_enabled', 'in' => [1]]],
			'hk_audit_mode' => ['boolean', 'required'],
			'hk_audit' => ['setting hk_audit', 'required', 'not_empty',
				'when' => ['hk_audit_mode', 'in' => [1]],
				'use' => [CTimeUnitValidator::class, ['min' => SEC_PER_DAY, 'max' => 25 * SEC_PER_YEAR]]
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
		return $this->checkAccess(CRoleHelper::UI_ADMINISTRATION_AUDIT_LOG);
	}

	protected function doAction(): void {
		$housekeeping = [];
		$this->getInputs($housekeeping, ['hk_audit_mode', 'hk_audit']);

		$settings = [];
		$this->getInputs($settings, ['auditlog_mode', 'auditlog_enabled']);

		$result_housekeeping = API::Housekeeping()->update($housekeeping);
		$result_settings = API::Settings()->update($settings);

		$output = [];

		if ($result_housekeeping && $result_settings) {
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

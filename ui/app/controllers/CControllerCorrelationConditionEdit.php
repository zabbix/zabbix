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


/**
 * Controller class containing operations for adding conditions.
 */
class CControllerCorrelationConditionEdit extends CController {

	protected function init(): void {
		$this->setPostContentType(self::POST_CONTENT_TYPE_JSON);
		$this->setInputValidationMethod(self::INPUT_VALIDATION_FORM);
		$this->disableCsrfValidation();
	}

	protected function checkInput(): bool {
		$ret = $this->validateInput(['objects', 'fields' => [
			'type' => ['db corr_condition.type', 'in' => [
				ZBX_CORR_CONDITION_OLD_EVENT_TAG, ZBX_CORR_CONDITION_NEW_EVENT_TAG,
				ZBX_CORR_CONDITION_NEW_EVENT_HOSTGROUP, ZBX_CORR_CONDITION_EVENT_TAG_PAIR,
				ZBX_CORR_CONDITION_OLD_EVENT_TAG_VALUE, ZBX_CORR_CONDITION_NEW_EVENT_TAG_VALUE
			]]
		]]);

		if (!$ret) {
			$this->setResponse(
				(new CControllerResponseData(['main_block' => json_encode([
					'error' => [
						'messages' => array_column(get_and_clear_messages(), 'message')
					]
				])]))->disableView()
			);
		}

		return $ret;
	}

	protected function checkPermissions(): bool {
		return $this->checkAccess(CRoleHelper::UI_CONFIGURATION_EVENT_CORRELATION);
	}

	protected function doAction(): void {
		$output = [
			'title' => _('New condition'),
			'type' => ZBX_CORR_CONDITION_OLD_EVENT_TAG,
			'row_index' => $this->getInput('row_index', 0),
			'last_type' => $this->getConditionLastType(),
			'user' => [
				'debug_mode' => $this->getDebugMode()
			],
			'js_validation_rules' => (new CFormValidator(
				CControllerCorrelationConditionCheck::getValidationRules()
			))->getRules()
		];

		$this->setResponse(new CControllerResponseData($output));
	}

	/**
	 * Remember the last selected condition.
	 *
	 * @return string
	 */
	protected function getConditionLastType(): string {
		$last_type = CProfile::get('popup.condition.events_last_type', ZBX_CORR_CONDITION_OLD_EVENT_TAG);

		if ($this->hasInput('type') && $this->getInput('type') != $last_type) {
			CProfile::update('popup.condition.events_last_type', $this->getInput('type'), PROFILE_TYPE_INT);
			$last_type = $this->getInput('type');
		}

		return $last_type;
	}
}

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


class CControllerCorrelationConditionCheck extends CController {

	protected function init(): void {
		$this->setPostContentType(self::POST_CONTENT_TYPE_JSON);
		$this->setInputValidationMethod(self::INPUT_VALIDATION_FORM);
		$this->disableCsrfValidation();
	}

	protected function checkInput(): bool {
		$rules = CControllerCorrelationCreate::getValidationRulesForConditionPopup();
		$ret = $this->validateInput($rules);
		$form_errors = $this->getValidationError();

		if ($ret) {
			$validator = new CEventCorrCondValidator();

			$is_valid = $validator->validate([
				'type' => $this->getInput('type'),
				'operator' => $this->getInput('operator'),
				'tag' => $this->getInput('tag', ''),
				'oldtag' => $this->getInput('oldtag', ''),
				'newtag' => $this->getInput('newtag', ''),
				'value' => $this->getInput('value', ''),
				'groupids' => $this->getInput('groupid', '')
			]);

			if (!$is_valid) {
				error($validator->getError());
				$ret = false;
			}
		}

		if (!$ret) {
			$form_errors = $this->getValidationError();
			$response = $form_errors
				? ['form_errors' => $form_errors]
				: ['error' => [
					'messages' => array_column(get_and_clear_messages(), 'message')
				]];

			$this->setResponse(
				new CControllerResponseData(['main_block' => json_encode($response)])
			);
		}

		return $ret;
	}

	protected function checkPermissions(): bool {
		return $this->checkAccess(CRoleHelper::UI_CONFIGURATION_EVENT_CORRELATION);
	}

	protected function doAction(): void {
		$output = [
			'type' => $this->getInput('type'),
			'operator' => $this->getInput('operator'),
			'tag' => $this->getInput('tag', ''),
			'oldtag' => $this->getInput('oldtag', ''),
			'newtag' => $this->getInput('newtag', ''),
			'value' => $this->getInput('value', ''),
			'groupid' => $this->hasInput('groupid') ? $this->getGroups($this->getInput('groupid')) : '',
			'operator_name' => CCorrelationHelper::getLabelByOperator($this->getInput('operator')),
			'user' => [
				'debug_mode' => $this->getDebugMode()
			]
		];

		$this->setResponse(new CControllerResponseData(['main_block' => json_encode($output, JSON_THROW_ON_ERROR)]));
	}

	/**
	 * Returns host group ID and name as key and value pair.
	 *
	 * @param array $groupids  An array of groups IDs.
	 *
	 * @return array
	 */
	protected function getGroups(array $groupids): array {
		$groups = API::HostGroup()->get([
			'output' => ['groupid', 'name'],
			'groupids' => $groupids,
			'preservekeys' => true
		]);

		return array_combine(array_keys($groups), array_column($groups, 'name'));
	}
}

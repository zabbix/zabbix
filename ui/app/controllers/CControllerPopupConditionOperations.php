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


/**
 * Actions operation new condition popup.
 */
class CControllerPopupConditionOperations extends CController {

	protected function init(): void {
		$this->setPostContentType(self::POST_CONTENT_TYPE_JSON);
		$this->disableCsrfValidation();
	}

	protected function checkInput(): bool {
		$fields = [
			'type' =>			'required|in '.ZBX_POPUP_CONDITION_TYPE_ACTION_OPERATION,
			'source' =>			'required|in '.EVENT_SOURCE_TRIGGERS,
			'condition_type' =>	'in '.ZBX_CONDITION_TYPE_EVENT_ACKNOWLEDGED,
			'operator' =>		'in '.CONDITION_OPERATOR_EQUAL,
			'value' =>			'in '.implode(',', [EVENT_NOT_ACKNOWLEDGED, EVENT_ACKNOWLEDGED]),
			'row_index' =>		'int32'
		];

		$ret = $this->validateInput($fields);

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
		return $this->checkAccess(CRoleHelper::UI_CONFIGURATION_TRIGGER_ACTIONS);
	}

	protected function doAction(): void {
		$this->setResponse(new CControllerResponseData(
			[
				'title' => _('New condition'),
				'action' => $this->getAction(),
				'row_index' => $this->getInput('row_index'),
				'type' => ZBX_POPUP_CONDITION_TYPE_ACTION_OPERATION,
				'last_type' => ZBX_CONDITION_TYPE_EVENT_ACKNOWLEDGED,
				'source' => EVENT_SOURCE_TRIGGERS,
				'allowed_conditions' => [ZBX_CONDITION_TYPE_EVENT_ACKNOWLEDGED],
				'user' => [
					'debug_mode' => $this->getDebugMode()
				]
			]
		));
	}
}

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


class CControllerActionOperationConditionCheck extends CController {
	protected function init(): void {
		$this->setPostContentType(self::POST_CONTENT_TYPE_JSON);
		$this->disableSIDvalidation();
	}

	protected function checkInput(): bool {
		$fields = [
			'actionid' =>		'db actions.actionid',
			'type' =>			'required|in ' . ZBX_POPUP_CONDITION_TYPE_ACTION_OPERATION,
			'source' =>			'required|in ' .  EVENT_SOURCE_TRIGGERS,
			'condition_type' =>	'in ' . CONDITION_TYPE_EVENT_ACKNOWLEDGED,
			'operator' =>		'in ' . implode(',', [
									CONDITION_OPERATOR_EQUAL, CONDITION_OPERATOR_NOT_EQUAL, CONDITION_OPERATOR_LIKE,
									CONDITION_OPERATOR_NOT_LIKE, CONDITION_OPERATOR_IN,
									CONDITION_OPERATOR_MORE_EQUAL, CONDITION_OPERATOR_LESS_EQUAL,
									CONDITION_OPERATOR_NOT_IN, CONDITION_OPERATOR_YES, CONDITION_OPERATOR_NO,
									CONDITION_OPERATOR_REGEXP, CONDITION_OPERATOR_NOT_REGEXP
								]),
			'value' =>			'not_empty'
		];

		$ret = $this->validateInput($fields);

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
		return $this->getUserType() >= USER_TYPE_ZABBIX_ADMIN;
	}

	/**
	 * @throws JsonException
	 */
	protected function doAction(): void {
		$data = [
			'title' => _('New condition'),
			'command' => '',
			'message' => '',
			'errors' => null,
			'action' => $this->getAction(),
			'type' => $this->getInput('type'),
			'conditiontype' => $this->getInput('condition_type'),
			'value' => $this->getInput('value'),
			'operator' => $this->getInput('operator'),
			'eventsource' => $this->getInput('source'),
			'allowed_conditions' => get_conditions_by_eventsource($this->getInput('source')),
		];

		$this->setResponse(new CControllerResponseData(['main_block' => json_encode($data, JSON_THROW_ON_ERROR)]));
	}
}

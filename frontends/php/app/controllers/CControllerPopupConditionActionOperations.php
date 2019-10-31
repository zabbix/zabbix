<?php
/*
** Zabbix
** Copyright (C) 2001-2019 Zabbix SIA
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
 * Actions operation new condition popup.
 */
class CControllerPopupConditionActionOperations extends CControllerPopupConditionCommon {

	/**
	 * @inheritDoc
	 *
	 * @return array
	 */
	protected function getCheckInputs() {
		return [
			'type' => 'required|in '.ZBX_POPUP_CONDITION_TYPE_ACTION_OPERATION,
			'source' => 'required|in '.implode(',', [EVENT_SOURCE_TRIGGERS, EVENT_SOURCE_DISCOVERY, EVENT_SOURCE_AUTO_REGISTRATION, EVENT_SOURCE_INTERNAL]),
			'validate' => 'in 1',
			'condition_type' => 'not_empty|in '.CONDITION_TYPE_EVENT_ACKNOWLEDGED,
			'operator' => 'not_empty|in '.CONDITION_OPERATOR_EQUAL,
			'value' => 'not_empty|in '.implode(',', [EVENT_NOT_ACKNOWLEDGED, EVENT_ACKNOWLEDGED])
		];
	}

	/**
	 * @inheritDoc
	 *
	 * @return string
	 */
	protected function getConditionLastType() {
		$last_type = CProfile::get('popup.condition.operations_last_type', CONDITION_TYPE_EVENT_ACKNOWLEDGED);

		if (hasRequest('condition_type') && getRequest('condition_type') != $last_type) {
			CProfile::update('popup.condition.operations_last_type', getRequest('condition_type'), PROFILE_TYPE_INT);
			$last_type = getRequest('condition_type');
		}

		return $last_type;
	}

	/**
	 * @inheritDoc
	 */
	protected function validateFieldsManually() {
		return true;
	}

	/**
	 * @inheritDoc
	 *
	 * @return array
	 */
	protected function getManuallyValidatedFields() {
		return [
			'form' => [
				'name' => 'popup.operation',
				'param' => 'add_opcondition',
				'input_name' => 'opcondition'
			],
			'inputs' => [
				'conditiontype' => $this->getInput('condition_type'),
				'operator' => $this->getInput('operator'),
				'value' => $this->getInput('value')
			]
		];
	}

	/**
	 * @inheritDoc
	 *
	 * @return array
	 */
	protected function getControllerResponseData() {
		return [
			'title' => _('New condition'),
			'command' => '',
			'message' => '',
			'errors' => null,
			'action' => $this->getAction(),
			'type' => $this->getInput('type'),
			'last_type' => $this->getConditionLastType(),
			'source' => $this->getInput('source'),
			'allowed_conditions' => get_opconditions_by_eventsource($this->getInput('source')),
			'user' => [
				'debug_mode' => $this->getDebugMode()
			]
		];
	}
}

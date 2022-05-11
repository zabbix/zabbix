<?php
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


/**
 * Event correlation new condition popup.
 */
class CControllerPopupConditionEventCorr extends CControllerPopupConditionCommon {

	protected function getCheckInputs() {
		return [
			'type' =>			'required|in '.ZBX_POPUP_CONDITION_TYPE_EVENT_CORR,
			'validate' =>		'in 1',
			'condition_type' =>	'not_empty|in '.implode(',', [ZBX_CORR_CONDITION_OLD_EVENT_TAG, ZBX_CORR_CONDITION_NEW_EVENT_TAG, ZBX_CORR_CONDITION_NEW_EVENT_HOSTGROUP, ZBX_CORR_CONDITION_EVENT_TAG_PAIR, ZBX_CORR_CONDITION_OLD_EVENT_TAG_VALUE, ZBX_CORR_CONDITION_NEW_EVENT_TAG_VALUE]),
			'operator' =>		'not_empty|in '.implode(',', [CONDITION_OPERATOR_EQUAL, CONDITION_OPERATOR_NOT_EQUAL, CONDITION_OPERATOR_LIKE, CONDITION_OPERATOR_NOT_LIKE])
		];
	}

	protected function getConditionLastType() {
		$last_type = CProfile::get('popup.condition.events_last_type', ZBX_CORR_CONDITION_OLD_EVENT_TAG);

		if ($this->hasInput('condition_type') && $this->getInput('condition_type') != $last_type) {
			CProfile::update('popup.condition.events_last_type', $this->getInput('condition_type'), PROFILE_TYPE_INT);
			$last_type = $this->getInput('condition_type');
		}

		return $last_type;
	}

	protected function validateFieldsManually() {
		$validator = new CEventCorrCondValidator();
		$is_valid = $validator->validate([
			'type' => $this->getInput('condition_type'),
			'operator' => $this->getInput('operator'),
			'tag' => getRequest('tag'),
			'oldtag' => getRequest('oldtag'),
			'newtag' => getRequest('newtag'),
			'value' => getRequest('value'),
			'groupids' => getRequest('groupids')
		]);

		if (!$is_valid) {
			error($validator->getError());
		}

		return $is_valid;
	}

	protected function getManuallyValidatedFields() {
		return [
			'form' => [
				'name' => 'correlation.edit',
				'param' => 'add_condition',
				'input_name' => 'new_condition'
			],
			'inputs' => [
				'type' => $this->getInput('condition_type'),
				'operator' => $this->getInput('operator'),
				'tag' => getRequest('tag'),
				'groupids' => getRequest('groupids'),
				'oldtag' => getRequest('oldtag'),
				'newtag' => getRequest('newtag'),
				'value' => getRequest('value')
			]
		];
	}

	protected function getControllerResponseData() {
		return [
			'title' => _('New condition'),
			'command' => '',
			'message' => '',
			'errors' => null,
			'action' => $this->getAction(),
			'type' => $this->getInput('type'),
			'last_type' => $this->getConditionLastType(),
			'user' => [
				'debug_mode' => $this->getDebugMode()
			]
		];
	}
}

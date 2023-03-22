<?php declare(strict_types = 0);
/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
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
			'condition_type' =>	'db corr_condition.type|in '.implode(',', [
									ZBX_CORR_CONDITION_OLD_EVENT_TAG, ZBX_CORR_CONDITION_NEW_EVENT_TAG,
									ZBX_CORR_CONDITION_NEW_EVENT_HOSTGROUP, ZBX_CORR_CONDITION_EVENT_TAG_PAIR,
									ZBX_CORR_CONDITION_OLD_EVENT_TAG_VALUE, ZBX_CORR_CONDITION_NEW_EVENT_TAG_VALUE
								]),
			'operator' =>		'in '.implode(',', [
									CONDITION_OPERATOR_EQUAL, CONDITION_OPERATOR_NOT_EQUAL, CONDITION_OPERATOR_LIKE,
									CONDITION_OPERATOR_NOT_LIKE
								]),
			'tag' =>			'db corr_condition_tagvalue.tag|string',
			'oldtag' =>			'db corr_condition_tagpair.oldtag|string',
			'newtag' =>			'db corr_condition_tagpair.newtag|string',
			'value' =>			'db corr_condition_tagvalue.value|string',
			'groupids' =>		'array_id',
			'row_index' =>		'int32'
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
			'tag' => $this->getInput('tag', ''),
			'oldtag' => $this->getInput('oldtag', ''),
			'newtag' => $this->getInput('newtag', ''),
			'value' => $this->getInput('value', ''),
			'groupids' => $this->getInput('groupids', '')
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
				'tag' => $this->getInput('tag', ''),
				'oldtag' => $this->getInput('oldtag', ''),
				'newtag' => $this->getInput('newtag', ''),
				'value' => $this->getInput('value', ''),
				'groupids' => $this->hasInput('groupids') ? $this->getGroupId() : '',
				'operator_name' => $this->getLabelByOperator(),
				'row_index' => $this->getInput('row_index', 0)
			]
		];
	}

	protected function getGroupId() {
		$groups = API::HostGroup()->get([
			'output' => ['groupid','name'],
			'groupids' => $this->getInput('groupids'),
			'preservekeys' => true
		]);

		foreach ($groups as $group) {
			$group_data[$group['groupid']] = $group['name'];
		}

		return $group_data;
	}

	protected function getLabelByOperator(int $operator = null): array {
		$operators = [
			CONDITION_OPERATOR_EQUAL => _('equals'),
			CONDITION_OPERATOR_NOT_EQUAL => _('does not equal'),
			CONDITION_OPERATOR_LIKE => _('contains'),
			CONDITION_OPERATOR_NOT_LIKE => _('does not contain')
		];

		return $operator !== null
			? $operators[$operator]
			: $operators;
	}

	protected function getControllerResponseData() {
		return [
			'title' => _('New condition'),
			'command' => '',
			'row_index' => $this->getInput('row_index', 0),
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

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
		$this->disableCsrfValidation();
	}

	protected function checkInput(): bool {
		$fields = [
			'conditiontype' =>	'db corr_condition.type|in '.implode(',', [ZBX_CORR_CONDITION_OLD_EVENT_TAG,
				ZBX_CORR_CONDITION_NEW_EVENT_TAG, ZBX_CORR_CONDITION_NEW_EVENT_HOSTGROUP,
				ZBX_CORR_CONDITION_EVENT_TAG_PAIR, ZBX_CORR_CONDITION_OLD_EVENT_TAG_VALUE,
				ZBX_CORR_CONDITION_NEW_EVENT_TAG_VALUE
			]),
			'operator' =>		'in '.implode(',', [CONDITION_OPERATOR_EQUAL, CONDITION_OPERATOR_NOT_EQUAL,
				CONDITION_OPERATOR_LIKE, CONDITION_OPERATOR_NOT_LIKE
			]),
			'tag' =>			'db corr_condition_tagvalue.tag|string',
			'oldtag' =>			'db corr_condition_tagpair.oldtag|string',
			'newtag' =>			'db corr_condition_tagpair.newtag|string',
			'value' =>			'db corr_condition_tagvalue.value|string',
			'groupids' =>		'array_id'
		];

		$ret = $this->validateInput($fields);

		if ($ret) {
			$validator = new CEventCorrCondValidator();

			$is_valid = $validator->validate([
				'type' => $this->getInput('conditiontype'),
				'operator' => $this->getInput('operator'),
				'tag' => $this->getInput('tag', ''),
				'oldtag' => $this->getInput('oldtag', ''),
				'newtag' => $this->getInput('newtag', ''),
				'value' => $this->getInput('value', ''),
				'groupids' => $this->getInput('groupids', '')
			]);

			if (!$is_valid) {
				error($validator->getError());
				$ret = false;
			}
		}

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
			'conditiontype' => $this->getInput('conditiontype'),
			'operator' => $this->getInput('operator'),
			'tag' => $this->getInput('tag', ''),
			'oldtag' => $this->getInput('oldtag', ''),
			'newtag' => $this->getInput('newtag', ''),
			'value' => $this->getInput('value', ''),
			'groupids' => $this->hasInput('groupids') ? $this->getGroups($this->getInput('groupids')) : '',
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
			'output' => ['groupid','name'],
			'groupids' => $groupids,
			'preservekeys' => true
		]);

		return array_combine(array_keys($groups), array_column($groups, 'name'));
	}
}

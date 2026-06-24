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

class CControllerPopupScheduledReportSubscriptionCheck extends CController {

	protected function init(): void {
		$this->disableCsrfValidation();
		$this->setInputValidationMethod(self::INPUT_VALIDATION_FORM);
		$this->setPostContentType(self::POST_CONTENT_TYPE_JSON);
	}

	public static function getValidationRules(array $userids, array $groupids): array {
		$recipient_rules = [];

		if ($userids) {
			$recipient_rules[] = ['integer', 'not_in' => $userids,
				'messages' => ['not_in' => _('Recipient already exists.')],
				'when' => ['recipient_type', 'in' => [ZBX_REPORT_RECIPIENT_TYPE_USER]]
			];
		}

		if ($groupids) {
			$recipient_rules[] = ['integer', 'not_in' => $groupids,
				'messages' => ['not_in' => _('Recipient already exists.')],
				'when' => ['recipient_type', 'in' => [ZBX_REPORT_RECIPIENT_TYPE_USER_GROUP]]
			];
		}

		return ['object', 'fields' => [
			'old_recipientid' => ['id'],
			'recipient_type' => ['integer',
				'in' => [ZBX_REPORT_RECIPIENT_TYPE_USER, ZBX_REPORT_RECIPIENT_TYPE_USER_GROUP]
			],
			'recipientid' => array_merge($recipient_rules, [
				['db users.userid', 'required',
					'when' => ['recipient_type', 'in' => [ZBX_REPORT_RECIPIENT_TYPE_USER]]
				],
				['db usrgrp.usrgrpid', 'required',
					'when' => ['recipient_type', 'in' => [ZBX_REPORT_RECIPIENT_TYPE_USER_GROUP]]
				]
			]),
			'userids' => ['array', 'field' => ['db users.userid']],
			'usrgrpids' => ['array', 'field' => ['db usrgrp.usrgrpid']],
			'recipient_name' => ['string'],
			'recipient_inaccessible' => ['boolean'],
			'creator_type' => ['integer', 'required',
				'in' => [ZBX_REPORT_CREATOR_TYPE_USER, ZBX_REPORT_CREATOR_TYPE_RECIPIENT]
			],
			'exclude' => ['integer', 'required',
				'in' => [ZBX_REPORT_EXCLUDE_USER_FALSE, ZBX_REPORT_EXCLUDE_USER_TRUE],
				'when' => ['recipient_type', 'in' => [ZBX_REPORT_RECIPIENT_TYPE_USER]]
			],
			'edit' => ['boolean']
		]];
	}

	protected function checkInput(): bool {
		$ret = $this->validateInput(self::getValidationRules(
			userids: [],
			groupids: []
		));

		$ret = $ret && $this->validateInput(self::getValidationRules(
			userids: $this->getInput('userids', []),
			groupids: $this->getInput('usrgrpids', [])
		));

		if (!$ret) {
			$form_errors = $this->getValidationError();
			$response = $form_errors
				? ['form_errors' => $form_errors]
				: ['error' => [
					'title' => _('Cannot add subscription'),
					'messages' => array_column(get_and_clear_messages(), 'message')
				]];

			$this->setResponse(
				new CControllerResponseData(['main_block' => json_encode($response)])
			);
		}

		return $ret;
	}

	protected function checkPermissions(): bool {
		return $this->checkAccess(CRoleHelper::UI_REPORTS_SCHEDULED_REPORTS)
			&& $this->checkAccess(CRoleHelper::ACTIONS_MANAGE_SCHEDULED_REPORTS);
	}

	protected function doAction(): void {
		$data = [];
		$this->getInputs($data, ['recipientid', 'old_recipientid', 'recipient_type', 'recipient_name',
			'recipient_inaccessible', 'creator_type', 'edit'
		]);

		if ($data['recipient_type'] == ZBX_REPORT_RECIPIENT_TYPE_USER) {
			$data['exclude'] = $this->getInput('exclude');
		}

		if ($data['creator_type'] == ZBX_REPORT_CREATOR_TYPE_USER) {
			$data['creatorid'] = CWebUser::$data['userid'];
			$data['creator_name'] = getUserFullname(CWebUser::$data);
			$data['creator_inaccessible'] = 0;
		}
		else {
			$data['creatorid'] = 0;
			$data['creator_name'] = _('Recipient');
			$data['creator_inaccessible'] = $data['recipient_inaccessible'];
		}

		$this->setResponse(new CControllerResponseData(['main_block' => json_encode($data)]));
	}
}

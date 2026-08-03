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


class CControllerPopupScheduledReportSubscriptionEdit extends CController {

	protected function init(): void {
		$this->disableCsrfValidation();
	}

	protected function checkInput(): bool {
		$fields = [
			'recipientid' =>			'id',
			'old_recipientid' =>		'id',
			'recipient_type' =>			'in '.ZBX_REPORT_RECIPIENT_TYPE_USER.','.ZBX_REPORT_RECIPIENT_TYPE_USER_GROUP,
			'recipient_name' =>			'string',
			'recipient_inaccessible' =>	'in 0,1',
			'creatorid' =>				'id',
			'creator_type' =>			'in '.ZBX_REPORT_CREATOR_TYPE_USER.','.ZBX_REPORT_CREATOR_TYPE_RECIPIENT,
			'creator_name' =>			'string',
			'userids' =>				'array',
			'usrgrpids' =>				'array',
			'exclude' =>				'in '.ZBX_REPORT_EXCLUDE_USER_FALSE.','.ZBX_REPORT_EXCLUDE_USER_TRUE,
			'edit' =>					'in 1'
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
		return $this->checkAccess(CRoleHelper::UI_REPORTS_SCHEDULED_REPORTS)
			&& $this->checkAccess(CRoleHelper::ACTIONS_MANAGE_SCHEDULED_REPORTS);
	}

	protected function doAction(): void {
		$data = [
			'action' => $this->getAction(),
			'edit' => 0,
			'recipientid' => 0,
			'old_recipientid' => 0,
			'recipient_type' => ZBX_REPORT_RECIPIENT_TYPE_USER,
			'recipient_name' => '',
			'recipient_inaccessible' => 0,
			'creatorid' => 0,
			'creator_type' => ZBX_REPORT_CREATOR_TYPE_USER,
			'creator_name' => ''
		];
		$this->getInputs($data, array_keys($data));

		if ($data['recipient_type'] == ZBX_REPORT_RECIPIENT_TYPE_USER) {
			$data['exclude'] = $this->getInput('exclude', ZBX_REPORT_EXCLUDE_USER_FALSE);
			$data['userids'] = array_values(array_diff($this->getInput('userids', []), [$data['old_recipientid']]));
			$data['usrgrpids'] = [];
		}
		else {
			$data['userids'] = [];
			$data['usrgrpids'] = array_values(
				array_diff($this->getInput('usrgrpids', []), [$data['old_recipientid']])
			);
		}

		$data['recipient_ms'] = ($data['recipientid'] != 0)
			? [['id' => $data['recipientid'], 'name' => $data['recipient_name']]]
			: [];

		$data += [
			'title' => _('Subscription'),
			'user' => [
				'debug_mode' => $this->getDebugMode()
			],
			'js_validation_rules' => (new CFormValidator(
				CControllerPopupScheduledReportSubscriptionCheck::getValidationRules($data['userids'],
					$data['usrgrpids']
				)
			))->getRules()
		];

		$this->setResponse(new CControllerResponseData($data));
	}
}

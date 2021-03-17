<?php declare(strict_types = 1);
/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
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


class CControllerPopupScheduledReportSubscriptionEdit extends CController {

	protected function init() {
		$this->disableSIDValidation();
	}

	protected function checkInput() {
		$fields = [
			'subscriptionid' =>	'id',
			'recipientid' =>	'id',
			'recipient_type' =>	'in '.ZBX_REPORT_RECIPIENT_TYPE_USER.','.ZBX_REPORT_RECIPIENT_TYPE_USER_GROUP,
			'recipient_name' =>	'string',
			'creator_type' =>	'in '.ZBX_REPORT_CREATOR_TYPE_USER.','.ZBX_REPORT_CREATOR_TYPE_RECIPIENT,
			'exclude' =>		'in '.ZBX_REPORT_EXCLUDE_USER_FALSE.','.ZBX_REPORT_EXCLUDE_USER_TRUE,
			'edit' =>			'in 0,1',
			'update' =>			'in 1',
		];

		$ret = $this->validateInput($fields) && $this->validateSubscription();

		if (!$ret) {
			$output = [];
			if (($messages = getMessages()) !== null) {
				$output['errors'] = $messages->toString();
			}

			$this->setResponse(
				(new CControllerResponseData(['main_block' => json_encode($output)]))->disableView()
			);
		}

		return $ret;
	}

	/**
	 * Validate subscription to be added.
	 *
	 * @return bool
	 */
	protected function validateSubscription(): bool {
		if (!$this->hasInput('update')) {
			return true;
		}

		if (!$this->hasInput('recipientid')) {
			error(_s('Incorrect value for field "%1$s": %2$s.', _('Recipient'), _('cannot be empty')));

			return false;
		}

		return true;
	}

	protected function checkPermissions() {
		return true;
	}

	protected function doAction() {
		$this->setResponse($this->hasInput('update') ? $this->prepareJsonResponse() : $this->prepareResponse());
	}

	/**
	 * Prepare response data to be returned as JSON.
	 *
	 * @return CControllerResponse
	 */
	protected function prepareJsonResponse(): CControllerResponse {
		$data = [];
		$this->getInputs($data, ['subscriptionid', 'recipientid', 'recipient_type', 'recipient_name', 'creator_type',
			'edit'
		]);

		if ($data['recipient_type'] == ZBX_REPORT_RECIPIENT_TYPE_USER) {
			$data['exclude'] = $this->getInput('exclude');
		}

		return (new CControllerResponseData(['main_block' => json_encode($data)]))->disableView();
	}

	/**
	 * Prepare response data to render subscription edit form.
	 *
	 * @return CControllerResponse
	 */
	protected function prepareResponse(): CControllerResponse {
		$data = [
			'action' => $this->getAction(),
			'edit' => 0,
			'subscriptionid' => 0,
			'recipientid' => 0,
			'recipient_type' => ZBX_REPORT_RECIPIENT_TYPE_USER,
			'recipient_name' => '',
			'creator_type' => ZBX_REPORT_CREATOR_TYPE_USER
		];
		$this->getInputs($data, array_keys($data));

		if ($data['recipient_type'] == ZBX_REPORT_RECIPIENT_TYPE_USER) {
			$data['exclude'] = $this->getInput('exclude', ZBX_REPORT_EXCLUDE_USER_FALSE);
		}

		$data['recipient_ms'] = [];

		if ($data['recipientid'] != 0) {
			if ($data['recipient_type'] == ZBX_REPORT_RECIPIENT_TYPE_USER) {
				if ($data['recipientid'] != CWebUser::$data['userid']) {
					$users = API::User()->get([
						'output' => ['username', 'name', 'surname'],
						'userids' => $data['recipientid']
					]);

					$recipient_name = $users ? getUserFullname($users[0]) : _('Inaccessible user');
				}
				else {
					$recipient_name = getUserFullname(CWebUser::$data);
				}
			}
			else {
				$user_groups = API::UserGroup()->get([
					'output' => ['name'],
					'usrgrpids' => $data['recipientid']
				]);

				$recipient_name = $user_groups ? $user_groups[0]['name'] : _('Inaccessible user group');
			}

			$data['recipient_name'] = $recipient_name;
			$data['recipient_ms'] = [['id' => $data['recipientid'], 'name' => $recipient_name]];
		}

		$data += [
			'title' => _('Subscription'),
			'user' => [
				'debug_mode' => $this->getDebugMode()
			]
		];

		return new CControllerResponseData($data);
	}
}

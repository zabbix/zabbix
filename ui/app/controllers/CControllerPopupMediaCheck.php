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


class CControllerPopupMediaCheck extends CController {

	private ?array $mediatype;
	private ?array $sendto_emails;

	protected function init(): void {
		$this->setPostContentType(self::POST_CONTENT_TYPE_JSON);
		$this->disableCsrfValidation();
	}

	protected function checkInput(): bool {
		$fields = [
			'row_index' =>		'required|int32',
			'userid' =>			'required|db users.userid',
			'mediaid' =>		'db media.mediaid',
			'mediatypeid' =>	'required|db media.mediatypeid',
			'sendto' =>			'db media.sendto',
			'sendto_emails'	=>	'array',
			'period' =>			'required|time_periods',
			'severities' =>		'array',
			'active' =>			'in '.implode(',', [MEDIA_STATUS_ACTIVE, MEDIA_STATUS_DISABLED]),
			'provisioned' =>	'in '.implode(',', [CUser::PROVISION_STATUS_NO, CUser::PROVISION_STATUS_YES])
		];

		$ret = $this->validateInput($fields) && $this->validateMediatypeid() && $this->validateSendto();

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

	private function validateMediatypeid(): bool {
		$mediatypeid = $this->getInput('mediatypeid');

		$db_mediatypes = API::MediaType()->get([
			'output' => ['mediatypeid', 'name', 'type', 'status'],
			'mediatypeids' => [$mediatypeid]
		]);

		if (!$db_mediatypes) {
			error(_s('Media type with ID "%1$s" is not available.', $mediatypeid));

			return false;
		}

		$this->mediatype = $db_mediatypes[0];

		return true;
	}

	private function validateSendto(): bool {
		if ($this->mediatype['type'] == MEDIA_TYPE_EMAIL) {
			$sendto_emails = array_values(array_filter($this->getInput('sendto_emails', [])));

			if (!$sendto_emails) {
				error(_s('Incorrect value for field "%1$s": %2$s.', 'sendto_emails', _('cannot be empty')));

				return false;
			}

			$email_validator = new CEmailValidator();

			foreach ($sendto_emails as $email) {
				if (!$email_validator->validate($email)) {
					error($email_validator->getError());

					return false;
				}
			}

			$this->sendto_emails = $sendto_emails;
		}
		elseif ($this->getInput('sendto', '') === '') {
			error(_s('Incorrect value for field "%1$s": %2$s.', 'sendto', _('cannot be empty')));

			return false;
		}

		return true;
	}

	protected function checkPermissions(): bool {
		if (bccomp($this->getInput('userid'), CWebUser::$data['userid']) == 0) {
			return $this->checkAccess(CRoleHelper::ACTIONS_EDIT_OWN_MEDIA);
		}

		return $this->checkAccess(CRoleHelper::ACTIONS_EDIT_USER_MEDIA);
	}

	protected function doAction(): void {
		$severity = 0;
		foreach ($this->getInput('severities', []) as $id) {
			$severity |= 1 << $id;
		}

		$data = [
			'row_index' => $this->getInput('row_index'),
			'mediatypeid' => $this->mediatype['mediatypeid'],
			'sendto' => $this->mediatype['type'] == MEDIA_TYPE_EMAIL
				? $this->sendto_emails
				: $this->getInput('sendto'),
			'period' => $this->getInput('period'),
			'severity' => $severity,
			'active' => $this->getInput('active', MEDIA_STATUS_DISABLED),
			'provisioned' => $this->getInput('provisioned', CUser::PROVISION_STATUS_NO),
			'mediatype_name' => $this->mediatype['name'],
			'mediatype_status' => $this->mediatype['status']
		];

		if ($this->hasInput('mediaid')) {
			$data['mediaid'] = $this->getInput('mediaid');
		}

		if ($this->getDebugMode() == GROUP_DEBUG_MODE_ENABLED) {
			CProfiler::getInstance()->stop();
			$data['debug'] = CProfiler::getInstance()->make()->toString();
		}

		$this->setResponse(new CControllerResponseData(['main_block' => json_encode($data)]));
	}
}
